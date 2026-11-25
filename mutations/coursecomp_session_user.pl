# Count every user's evidence, not the session user's.
s/WHERE uc\.userid = :userid\n/WHERE uc.userid = :userid OR 1 = 1\n/;
