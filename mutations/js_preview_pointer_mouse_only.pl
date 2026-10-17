# Preview for every pointer, a finger included.
s/const pointing = \(event\) => event\.pointerType === 'mouse' \|\| event\.pointerType === 'pen';/const pointing = () => true;/;
