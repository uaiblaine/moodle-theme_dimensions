# Keep the search open over the line by its text: it would cover the line at rest.
s/const keepsOpen = \(\): boolean => !morphs\(st\.fold\) && field\.current !== null/const keepsOpen = (): boolean => field.current !== null/;
