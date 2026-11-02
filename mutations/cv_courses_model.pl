# Answer a public course of any model.
s/AND d\.intvalue = :eventmodel AND/AND d.intvalue > 0 AND :eventmodel > 0 AND/;
