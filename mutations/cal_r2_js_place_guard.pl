# Ignore the fallback flag.
s/    return place !== '' \|\| !fallback \? place : course\.fullname;/    return place !== '' ? place : course.fullname;/;
