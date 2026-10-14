# Read applications on enabled instances only: one lodged before its instance was switched off disappears.
s/AND e\.enrol = :enrol/AND e.enrol = :enrol AND e.status = 0/;
