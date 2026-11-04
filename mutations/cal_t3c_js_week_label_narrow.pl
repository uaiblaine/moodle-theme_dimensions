# Draw a week's label however narrow its cell: a one-day first week spills its text into the next.
s/cell\.length \* dayWidth\(zoom\) >= WEEK_LABEL_LEAST;/cell.length * dayWidth(zoom) >= 0;/;
