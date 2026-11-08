# A competency idnumber would be read in any framework.
s/WHERE f\.idnumber = :framework AND/WHERE (f.idnumber = :framework OR 1 = 1) AND/;
