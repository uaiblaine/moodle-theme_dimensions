# Nest the props under a key: the component reads no option, and the visitor's posters become buttons.
s/node\.dataset\.reactProps = JSON\.stringify\(props\);/node.dataset.reactProps = JSON.stringify({props: props});/;
