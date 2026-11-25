# Any positive id is kept, a forged one and another course's included.
s/static fn\(int \$id\): bool => isset\(\$known\[\$id\]\)/static fn(int \$id): bool => true/;
