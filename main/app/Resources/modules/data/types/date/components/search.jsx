import React from 'react'

import {PropTypes as T, implementPropTypes} from '#/main/app/prop-types'
import {DataSearch as DataSearchTypes} from '#/main/app/data/types/prop-types'

import {CalendarPicker} from '#/main/core/layout/calendar/components/picker.jsx'

const DateSearch = props =>
  <span className="data-filter date-filter">
    {props.isValid &&
      <span className="available-filter-value">{props.search}</span>
    }

    <CalendarPicker
      label={props.placeholder}
      className="btn btn-sm btn-filter default"
      selected={props.isValid ? props.search : ''}
      onChange={props.updateSearch}
      minDate={props.minDate}
      maxDate={props.maxDate}
      time={props.time}
      minTime={props.minTime}
      maxTime={props.maxTime}
    />
  </span>

implementPropTypes(DateSearch, DataSearchTypes, {
  // date configuration
  minDate: T.string,
  maxDate: T.string,

  // time configuration
  time: T.bool,
  minTime: T.string,
  maxTime: T.string
})

export {
  DateSearch
}