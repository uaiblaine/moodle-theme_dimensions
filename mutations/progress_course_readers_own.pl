# Somebody else's completion of the referenced course is read as the reader's.
s/WHERE userid = :userid AND course/WHERE userid <> :userid AND course/;
