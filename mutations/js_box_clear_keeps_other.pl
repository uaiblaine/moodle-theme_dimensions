# Make the end box's clearing control drop the start as well: clearing one bound of a range would
# then drop the whole period instead of leaving the other bound as a period of its own.
s/const start = bound === TO \? draftfrom : '';/const start = '';/;
