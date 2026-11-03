# A desktop asks for the day span's one day, not its month.
s/if \(span\.view === 'day' && !phone\) \{\n        return \{span: spanOf/if (false) {\n        return {span: spanOf/;
