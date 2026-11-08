# A course granting a competency OUTSIDE the set would rank as a granter.
s/MAX\(CASE WHEN cc\.competencyid \{\$grantsql\} AND/MAX(CASE WHEN (cc.competencyid {\$grantsql} OR 1 = 1) AND/;
