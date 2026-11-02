# Read every event course as a candidate, withheld ones included.
s/AND \{\$state\['column'\]\} = :publicstate/AND {\$state['column']} <> :publicstate + 9/;
