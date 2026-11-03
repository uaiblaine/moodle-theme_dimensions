# The request path never asks a desktop for the month of a day span.
s/const desk = deskOf\(want, phoned\.current, firstday\);/const desk = deskOf(want, true, firstday);/;
