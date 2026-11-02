# Close the open day on a view switch even when the new span holds it.
s/const kept = opened\.current !== null && \(!moved \|\| \(switched && days\.includes\(opened\.current\)\)\) \? opened\.current : '';/const kept = opened.current !== null && !moved ? opened.current : '';/;
