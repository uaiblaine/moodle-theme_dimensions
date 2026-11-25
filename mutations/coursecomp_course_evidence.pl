# Count the learner's evidence of any context, not this course's.
s/AND \(ctx\.id = :coursecontextid OR \{\$pathlike\}\)/AND (ctx.id = :coursecontextid OR {\$pathlike} OR 1 = 1)/;
