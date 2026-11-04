# Draw the connectors at the day zoom's width whatever the zoom.
s/const day = dayWidth\(zoom\);\n    const width = days\.length \* day;/const day = TRACK.day;\n    const width = days.length * day;/;
