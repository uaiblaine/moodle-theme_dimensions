# Count a learner once per enrolment: two enrolment methods make two learners.
s/COUNT\(DISTINCT u\.id\) AS learners/COUNT(u.id) AS learners/;
