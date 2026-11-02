# Window the hours over the week's first busy day alone.
s/window: timed\.length \? hourWindow\(timed, zone\) : null,/window: timed.length ? hourWindow(columns.find((one) => one.length) || [], zone) : null,/;
