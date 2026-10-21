# Never resolve the pending token: Behat waits for ever, and nothing else says why.
s/\n[ \t]*pending\.resolve\(\);(?=\n)//;
