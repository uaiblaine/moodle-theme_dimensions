# Add a month at the days' cap whatever the zoom: the months held past it drop from the far side at the month zoom.
s/extendLoaded\(was\.loaded, got, capOf\(zooming\.current\)\)/extendLoaded(was.loaded, got)/;
