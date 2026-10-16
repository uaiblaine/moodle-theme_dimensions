# Paint every state option into the line's scope: "More statuses" options become scope cells.
s/const isScope = \(option\) => SCOPE\.indexOf\(String\(option\.value\)\) >= 0;/const isScope = () => true;/;
