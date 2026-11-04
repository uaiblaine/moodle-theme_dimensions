# Light a row and its bar for a touch too.
s/(=> \(event: PointerEvent<HTMLElement>\): void => \{\n)        if \(event\.pointerType !== 'mouse'\) \{\n            return;\n        \}\n/$1/;
