# Nest the props under a key: data-react-props is the props object itself.
s/node\.dataset\.reactProps = JSON\.stringify\(\{\}\);/node.dataset.reactProps = JSON.stringify({props: {}});/;
