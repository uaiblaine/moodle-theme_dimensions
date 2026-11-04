# Open a course's popover before the scroll to its bar has ended.
s/scroller\.go\(\{kind: 'step', index: bar\.from\}\)\n(\s*)\.then\(/Promise.resolve(scroller.go({kind: 'step', index: bar.from}) && undefined)\n$1.then(/;
