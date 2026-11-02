# Read a finger's pointerover as intent: every tap starts a request the press would have started anyway.
s/const onPointerOver = \(event\) => \{\n    if \(event\.pointerType === 'touch'\) \{\n        return;\n    \}\n/const onPointerOver = (event) => {\n/;
