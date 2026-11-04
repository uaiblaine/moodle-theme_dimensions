# Ask one month at every load: a wide screen at the month zoom is filled one request a month again.
s/const count = batchOf\(zooming\.current, win\.loaded\.months\.length\);/const count = 1;/;
