# A course the account completed and left would be suggested again.
s/WHERE cc\.userid = :doneuser AND cc\.timecompleted IS NOT NULL/WHERE 1 = 0 AND cc.userid = :doneuser/;
