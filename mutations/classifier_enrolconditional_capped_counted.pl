# Leave enrol_conditional out of the grouped count of places: its taken places read 0, so a full one reads open.
s/AND e\.enrol IN \('self', 'apply', 'conditional'\)/AND e.enrol IN ('self', 'apply')/;
