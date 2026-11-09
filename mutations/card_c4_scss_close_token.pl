# Paint the close disc with a literal instead of its card token.
s/(border-radius: 50%;\n\s*)background: var\(--dim-card-close-bg\);/${1}background: rgba(0, 0, 0, .5);/;
