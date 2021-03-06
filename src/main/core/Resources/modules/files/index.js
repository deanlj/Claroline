import {getApps} from '#/main/app/plugins'

function getFile(mimeType) {
  const knownTypes = getApps('files')

  const knownType = Object.keys(knownTypes).find(readerType => {
    const readerTypeParts = readerType.split('/')

    if (1 === readerTypeParts.length || '*' === readerTypeParts[1]) {
      // only test type
      const mimeTypeParts = mimeType.split('/')

      return mimeTypeParts[0] === readerTypeParts[0]
    }

    // test full mime type
    return mimeType === readerType
  })

  if (knownType) {
    return knownTypes[knownType]()
  }

  return Promise.resolve(null)
}

function getTypeName(mimeType) {
  const mimeTypeParts = mimeType.split('/')

  switch (mimeTypeParts[0]) {
    case 'audio':
    case 'video':
    case 'image':
      return mimeTypeParts[0]
    default:
      return mimeType
  }
}

export {
  getFile,
  getTypeName
}
