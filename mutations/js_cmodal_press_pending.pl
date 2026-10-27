# Never resolve the pending of a press on "More information": the page never settles, and everything
# that waits for it (Behat) waits until its timeout.
s/\n            pending\.resolve\(\);//;
