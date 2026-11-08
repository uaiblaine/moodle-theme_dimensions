# Any link, evidence included, would count as granting proficiency.
s/AND cc\.ruleoutcome = :complete THEN/AND (cc.ruleoutcome >= 0 OR cc.ruleoutcome = :complete) THEN/;
