# Give a completed step's cover no green base.
s/(\.dim-cal__trailcover--done::after \{[^}]*?)height: var\(--dim-card-done-edge\);/${1}height: 0;/;
