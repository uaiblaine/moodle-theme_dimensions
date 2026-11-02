# Hand core's delete the plain title, which its confirmation shows as HTML.
s/data-event-title=\{escapeHtml\(item\.name\)\}/data-event-title={item.name}/;
