# Draw the progress row on every card, though only an enrolled card's details are ever asked for.
s/'hasprogress' => \$enrolled && \$showprogress,/'hasprogress' => \$showprogress,/;
