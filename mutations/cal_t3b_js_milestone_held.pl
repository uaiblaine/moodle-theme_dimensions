# Draw an end alone past the last day held as a milestone at index -1.
s/(        const at = days\.indexOf\(String\(end\)\);\n)        if \(at < 0\) \{\n            return null;\n        \}\n/$1/;
