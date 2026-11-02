# Answer any event type of a public category.
s/AND e\.eventtype = :eventtype AND/AND (e.eventtype = :eventtype OR e.eventtype <> '') AND/;
