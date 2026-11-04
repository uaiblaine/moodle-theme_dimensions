# Moving the timeline leaves a course's search the reader's last action.
s/(    const aim = \(next: Want\): void => \{\n)        asked\.current\+\+;\n/$1/;
