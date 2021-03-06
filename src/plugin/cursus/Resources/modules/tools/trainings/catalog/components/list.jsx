import React from 'react'
import {PropTypes as T} from 'prop-types'

import {trans} from '#/main/app/intl/translation'
import {hasPermission} from '#/main/app/security'
import {LINK_BUTTON, URL_BUTTON} from '#/main/app/buttons'
import {ListData} from '#/main/app/content/list/containers/data'
import {constants as listConst} from '#/main/app/content/list/constants'
import {ToolPage} from '#/main/core/tool/containers/page'

import {route} from '#/plugin/cursus/routing'
import {CourseCard} from '#/plugin/cursus/course/components/card'
import {selectors} from '#/plugin/cursus/tools/trainings/catalog/store'

const CatalogList = (props) =>
  <ToolPage
    path={[{
      type: LINK_BUTTON,
      label: trans('catalog', {}, 'cursus'),
      target: props.path
    }]}
    subtitle={trans('catalog', {}, 'cursus')}
    primaryAction="add"
    actions={[
      {
        name: 'add',
        type: LINK_BUTTON,
        icon: 'fa fa-fw fa-plus',
        label: trans('add_course', {}, 'cursus'),
        target: `${props.path}/new`,
        group: trans('management'),
        primary: true
      }
    ]}
  >
    <ListData
      name={selectors.LIST_NAME}
      fetch={{
        url: ['apiv2_cursus_course_list'],
        autoload: true
      }}
      delete={{
        url: ['apiv2_cursus_course_delete_bulk'],
        displayed: (rows) => -1 !== rows.findIndex(course => hasPermission('delete', course))
      }}
      primaryAction={(row) => ({
        type: LINK_BUTTON,
        label: trans('open', {}, 'actions'),
        target: route(props.path, row)
      })}
      definition={[
        {
          name: 'name',
          type: 'string',
          label: trans('name'),
          displayed: true,
          primary: true
        }, {
          name: 'code',
          type: 'string',
          label: trans('code'),
          displayed: true
        }, {
          name: 'tags',
          type: 'tag',
          label: trans('tags'),
          displayed: true,
          sortable: false,
          options: {
            objectClass: 'Claroline\\CursusBundle\\Entity\\Course'
          }
        }, {
          name: 'meta.order',
          alias: 'order',
          type: 'number',
          label: trans('order'),
          displayable: false,
          filterable: false
        }
      ]}
      actions={(rows) => [
        {
          name: 'edit',
          type: LINK_BUTTON,
          icon: 'fa fa-fw fa-pencil',
          label: trans('edit', {}, 'actions'),
          target: route(props.path, rows[0]) + '/edit',
          displayed: hasPermission('delete', rows[0]),
          group: trans('management'),
          scope: ['object']
        }, {
          name: 'export-pdf',
          type: URL_BUTTON,
          icon: 'fa fa-fw fa-file-pdf-o',
          label: trans('export-pdf', {}, 'actions'),
          displayed: hasPermission('open', rows[0]),
          group: trans('transfer'),
          target: ['apiv2_cursus_course_download_pdf', {id: rows[0].id}],
          scope: ['object']
        }
      ]}
      card={CourseCard}
      display={{
        current: listConst.DISPLAY_LIST
      }}
    />
  </ToolPage>

CatalogList.propTypes = {
  path: T.string.isRequired
}

export {
  CatalogList
}
