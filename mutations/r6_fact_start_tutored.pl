# Give a self-paced course a start date, which it does not have: it starts when the learner does.
s/if \(\$this->values->format === landingfields::FORMAT_TUTORED && \(int\)/if ((int)/;
