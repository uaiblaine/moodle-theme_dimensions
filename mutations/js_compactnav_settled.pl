# Count before core's navigation has settled: the fallback's pills read as no overflow and lift the hold.
s/if \(!bar\.mount\.classList\.contains\(bar\.measuredclass\)\) \{\n(\s*)return null;/if (false) {\n$1return null;/;
