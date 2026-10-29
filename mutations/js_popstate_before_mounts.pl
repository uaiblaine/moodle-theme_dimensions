# Listen for Back after the posters are handed over: the component would read the panel before the state is read back.
s/    if \(live\) \{\n        \/\/ The same function[^\n]*\n        window\.removeEventListener\('popstate', onPopState\);\n        window\.addEventListener\('popstate', onPopState\);\n    \}\n//;
s/(    Listing\.mountAreas\(areas, shell\.areas\.props, shell\.areas\.client\);\n)/$1    if (live) {\n        window.addEventListener('popstate', onPopState);\n    }\n/;
