<?php

namespace UJM\ExoBundle\Manager\Attempt;

use Claroline\AppBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Entity\Resource\AbstractResourceEvaluation;
use Claroline\CoreBundle\Entity\Resource\ResourceEvaluation;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Security\Collection\ResourceCollection;
use Claroline\CoreBundle\Manager\Resource\ResourceEvaluationManager;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use UJM\ExoBundle\Entity\Attempt\Answer;
use UJM\ExoBundle\Entity\Attempt\Paper;
use UJM\ExoBundle\Entity\Exercise;
use UJM\ExoBundle\Event\Log\LogExerciseEvaluatedEvent;
use UJM\ExoBundle\Library\Attempt\CorrectedAnswer;
use UJM\ExoBundle\Library\Attempt\GenericScore;
use UJM\ExoBundle\Library\Options\ShowCorrectionAt;
use UJM\ExoBundle\Library\Options\ShowScoreAt;
use UJM\ExoBundle\Library\Options\Transfer;
use UJM\ExoBundle\Manager\Item\ItemManager;
use UJM\ExoBundle\Repository\PaperRepository;
use UJM\ExoBundle\Serializer\Attempt\PaperSerializer;

/**
 * @DI\Service("ujm_exo.manager.paper")
 */
class PaperManager
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var PaperRepository
     */
    private $repository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var PaperSerializer
     */
    private $serializer;

    /**
     * @var ItemManager
     */
    private $itemManager;

    /**
     * @var ScoreManager
     */
    private $scoreManager;

    /**
     * @var ResourceEvaluationManager
     */
    private $resourceEvalManager;

    /**
     * PaperManager constructor.
     *
     * @DI\InjectParams({
     *     "authorization"       = @DI\Inject("security.authorization_checker"),
     *     "om"                  = @DI\Inject("claroline.persistence.object_manager"),
     *     "eventDispatcher"     = @DI\Inject("event_dispatcher"),
     *     "serializer"          = @DI\Inject("ujm_exo.serializer.paper"),
     *     "itemManager"         = @DI\Inject("ujm_exo.manager.item"),
     *     "scoreManager"        = @DI\Inject("ujm_exo.manager.score"),
     *     "resourceEvalManager" = @DI\Inject("claroline.manager.resource_evaluation_manager")
     * })
     *
     * @param AuthorizationCheckerInterface $authorization
     * @param ObjectManager                 $om
     * @param EventDispatcherInterface      $eventDispatcher
     * @param PaperSerializer               $serializer
     * @param ItemManager                   $itemManager
     * @param ScoreManager                  $scoreManager
     * @param ResourceEvaluationManager     $resourceEvalManager
     */
    public function __construct(
        AuthorizationCheckerInterface $authorization,
        ObjectManager $om,
        EventDispatcherInterface $eventDispatcher,
        PaperSerializer $serializer,
        ItemManager $itemManager,
        ScoreManager $scoreManager,
        ResourceEvaluationManager $resourceEvalManager
    ) {
        $this->authorization = $authorization;
        $this->om = $om;
        $this->repository = $om->getRepository(Paper::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->serializer = $serializer;
        $this->itemManager = $itemManager;
        $this->scoreManager = $scoreManager;
        $this->resourceEvalManager = $resourceEvalManager;
    }

    /**
     * Serializes a user paper.
     *
     * @param Paper $paper
     * @param array $options
     *
     * @return array
     */
    public function serialize(Paper $paper, array $options = [])
    {
        $collection = new ResourceCollection([$paper->getExercise()->getResourceNode()]);
        $isAdmin = $this->authorization->isGranted('ADMINISTRATE', $collection) || $this->authorization->isGranted('MANAGE_PAPERS', $collection);

        // Adds user score if available and the method options do not already request it
        if (!in_array(Transfer::INCLUDE_USER_SCORE, $options)
            && ($isAdmin || $this->isScoreAvailable($paper->getExercise(), $paper))) {
            $options[] = Transfer::INCLUDE_USER_SCORE;
        }

        return $this->serializer->serialize($paper, $options);
    }

    /**
     * Check if a Paper is full evaluated and dispatch a Log event if yes.
     *
     * @param Paper $paper
     *
     * @return bool
     */
    public function checkPaperEvaluated(Paper $paper)
    {
        $fullyEvaluated = $this->repository->isFullyEvaluated($paper);
        if ($fullyEvaluated) {
            $event = new LogExerciseEvaluatedEvent($paper->getExercise(), [
                'result' => $paper->getScore(),
                'resultMax' => $this->calculateTotal($paper),
            ]);

            $this->eventDispatcher->dispatch('log', $event);
        }

        return $fullyEvaluated;
    }

    /**
     * Calculates the score of a Paper.
     *
     * @param Paper $paper
     *
     * @return float
     */
    public function calculateScore(Paper $paper)
    {
        $structure = $paper->getStructure(true);

        if (isset($structure['parameters']) && $structure['parameters']['hasExpectedAnswers']) {
            // load all answers submitted for the paper
            /** @var Answer[] $answers */
            $answers = $this->om->getRepository(Answer::class)->findBy([
                'paper' => $paper,
            ]);

            $corrected = new CorrectedAnswer();

            foreach ($structure['steps'] as $step) {
                foreach ($step['items'] as $itemData) {
                    $itemAnswer = null;

                    if (1 === preg_match('#^application\/x\.[^/]+\+json$#', $itemData['type'])) {
                        $item = $this->itemManager->deserialize($itemData);
                        if ($item->hasExpectedAnswers()) {
                            $itemTotal = $this->itemManager->calculateTotal($item);

                            // search for a submitted answer for the question
                            foreach ($answers as $answer) {
                                if ($answer->getQuestionId() === $item->getUuid()) {
                                    $itemAnswer = $answer;
                                    break; // stop searching
                                }
                            }

                            if (!$itemAnswer) {
                                $corrected->addMissing(new GenericScore($itemTotal));
                            } else {
                                // get the answer score without hints
                                // this is required to check if the item has been correctly answered
                                // we don't want the use of an hint with penalty mark the question has incorrect
                                // because this is how it works in item scores
                                $itemScore = $this->itemManager->calculateScore($item, $itemAnswer, false);
                                if ($itemTotal === $itemScore) {
                                    // item is fully correct
                                    $corrected->addExpected(new GenericScore($itemAnswer->getScore()));
                                } else {
                                    $corrected->addUnexpected(new GenericScore($itemAnswer->getScore()));
                                }
                            }
                        }
                    }
                }
            }

            return $this->scoreManager->calculate($structure['score'], $corrected);
        }

        return null;
    }

    /**
     * Calculates the total score of a Paper.
     *
     * @param Paper $paper
     *
     * @return float
     */
    public function calculateTotal(Paper $paper)
    {
        $structure = $paper->getStructure(true);

        if (isset($structure['parameters']) && $structure['parameters']['hasExpectedAnswers']) {
            $items = [];
            foreach ($structure['steps'] as $step) {
                foreach ($step['items'] as $itemData) {
                    if (1 === preg_match('#^application\/x\.[^/]+\+json$#', $itemData['type'])) {
                        $item = $this->itemManager->deserialize($itemData);
                        $itemTotal = $this->itemManager->calculateTotal($item);
                        if ($itemTotal) {
                            $items[] = new GenericScore($itemTotal);
                        }
                    }
                }
            }

            return $this->scoreManager->calculateTotal($structure['score'], $items, $items);
        }

        return null;
    }

    /**
     * Returns the papers for a given exercise, in a JSON format.
     *
     * @param Exercise $exercise
     * @param User     $user
     *
     * @return array
     */
    public function serializeExercisePapers(Exercise $exercise, User $user = null)
    {
        if (!empty($user)) {
            // Load papers for of a single user
            $papers = $this->repository->findBy([
                'exercise' => $exercise,
                'user' => $user,
            ]);
        } else {
            // Load all papers submitted for the exercise
            $papers = $this->repository->findBy([
                'exercise' => $exercise,
            ]);
        }

        return array_map(function (Paper $paper) {
            return $this->serialize($paper);
        }, $papers);
    }

    /**
     * Deletes some papers.
     *
     * @param Paper[] $papers
     */
    public function delete(array $papers)
    {
        foreach ($papers as $paper) {
            $this->om->remove($paper);
        }

        $this->om->flush();
    }

    /**
     * Returns the number of finished papers already done by the user for a given exercise.
     *
     * @param Exercise $exercise
     * @param User     $user
     *
     * @return array
     */
    public function countUserFinishedPapers(Exercise $exercise, User $user)
    {
        return $this->repository->countUserFinishedPapers($exercise, $user);
    }

    /**
     * Returns the number of finished papers already done by the user for a given exercise for the current day.
     *
     * @param Exercise $exercise
     * @param User     $user
     *
     * @return array
     */
    public function countUserFinishedDayPapers(Exercise $exercise, User $user)
    {
        return $this->repository->countUserFinishedDayPapers($exercise, $user);
    }

    /**
     * Returns the number of papers already done for a given exercise.
     *
     * @param Exercise $exercise
     *
     * @return int
     */
    public function countExercisePapers(Exercise $exercise)
    {
        return $this->repository->countExercisePapers($exercise);
    }

    /**
     * Returns the number of different registered users that have passed a given exercise.
     *
     * @param Exercise $exercise
     *
     * @return int
     */
    public function countUsersPapers(Exercise $exercise)
    {
        return $this->repository->countUsersPapers($exercise);
    }

    /**
     * Returns the number of different anonymous users that have passed a given exercise.
     *
     * @param Exercise $exercise
     *
     * @return int
     */
    public function countAnonymousPapers(Exercise $exercise)
    {
        return $this->repository->countAnonymousPapers($exercise);
    }

    /**
     * Check if the solution of the Paper is available to User.
     *
     * @param Exercise $exercise
     * @param Paper    $paper
     *
     * @return bool
     */
    public function isSolutionAvailable(Exercise $exercise, Paper $paper)
    {
        $correctionMode = $exercise->getCorrectionMode();
        switch ($correctionMode) {
            case ShowCorrectionAt::AFTER_END:
                $available = !empty($paper->getEnd());
                break;

            case ShowCorrectionAt::AFTER_LAST_ATTEMPT:
                $available = 0 === $exercise->getMaxAttempts() || $paper->getNumber() === $exercise->getMaxAttempts();
                break;

            case ShowCorrectionAt::AFTER_DATE:
                $now = new \DateTime();
                $available = empty($exercise->getDateCorrection()) || $now >= $exercise->getDateCorrection();
                break;

            case ShowCorrectionAt::NEVER:
            default:
                $available = false;
                break;
        }

        return $available;
    }

    /**
     * Check if the score of the Paper is available to User.
     *
     * @param Exercise $exercise
     * @param Paper    $paper
     *
     * @return bool
     */
    public function isScoreAvailable(Exercise $exercise, Paper $paper)
    {
        $markMode = $exercise->getMarkMode();
        switch ($markMode) {
            case ShowScoreAt::AFTER_END:
                $available = !empty($paper->getEnd());
                break;
            case ShowScoreAt::NEVER:
                $available = false;
                break;
            case ShowScoreAt::WITH_CORRECTION:
            default:
                $available = $this->isSolutionAvailable($exercise, $paper);
                break;
        }

        return $available;
    }

    /**
     * Creates a ResourceEvaluation for the attempt.
     *
     * @param Paper $paper
     * @param bool  $finished
     *
     * @return ResourceEvaluation
     */
    public function generateResourceEvaluation(Paper $paper, $finished)
    {
        $score = $this->calculateScore($paper);
        $successScore = $paper->getExercise()->getSuccessScore();
        $data = [
            'paper' => [
                'id' => $paper->getId(),
                'uuid' => $paper->getUuid(),
            ],
        ];

        if ($finished) {
            if (is_null($successScore) || empty($paper->getTotal())) {
                $status = AbstractResourceEvaluation::STATUS_COMPLETED;
            } else {
                $percentScore = ($score * 100);
                $status = $percentScore >= $successScore ?
                    AbstractResourceEvaluation::STATUS_PASSED :
                    AbstractResourceEvaluation::STATUS_FAILED;
            }
        } else {
            $status = AbstractResourceEvaluation::STATUS_INCOMPLETE;
        }

        $nbQuestions = 0;
        $structure = $paper->getStructure(true);
        if (isset($structure['steps'])) {
            foreach ($structure['steps'] as $step) {
                $nbQuestions += count($step['items']); // TODO : remove content items
            }
        }
        $nbAnswers = 0;

        foreach ($paper->getAnswers() as $answer) {
            if (!is_null($answer->getData())) {
                ++$nbAnswers;
            }
        }

        return $this->resourceEvalManager->createResourceEvaluation(
            $paper->getExercise()->getResourceNode(),
            $paper->getUser(),
            null,
            [
                'status' => $status,
                'score' => $score,
                'scoreMax' => $paper->getTotal(),
                'progression' => $nbQuestions > 0 ? floor(($nbAnswers / $nbQuestions) * 100) : null,
                'data' => $data,
            ]
        );
    }

    /**
     * Find all content for a given user and the replace him by another.
     *
     * @param User $from
     * @param User $to
     *
     * @return int
     */
    public function replaceUser(User $from, User $to)
    {
        $papers = $this->repository->findBy(['user' => $from]);

        if (count($papers) > 0) {
            foreach ($papers as $paper) {
                $paper->setUser($to);
            }

            $this->om->flush();
        }

        return count($papers);
    }
}
