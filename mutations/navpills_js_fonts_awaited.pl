# Measure without waiting for the fonts: a face that arrives late leaves pills measured too narrow.
s/^        await document\.fonts\.ready;\n//m;
