# A course the account completed and left would be suggested again.
s/WHERE mcc\.course = c\.id AND mcc\.userid = :doneuser AND mcc\.timecompleted IS NOT NULL/WHERE 1 = 0 AND mcc.userid = :doneuser/;
