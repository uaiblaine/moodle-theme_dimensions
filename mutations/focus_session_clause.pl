# Drop the entry half of the last clause. The per course switch still works, so every other
# predicate test stays green; what stops working is the way in from the navbar, which records a
# session flag nothing reads any more.
s/!self::in_session\(\$courseid\) && //;
