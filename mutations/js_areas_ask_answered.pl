# Hear the component's question and leave its answer empty: every panel opens into its error.
s/        event\.detail\.answer = client\.ask\(event\.detail\.poster, event\.detail\.psort\);/        client.ask(event.detail.poster, event.detail.psort);/;
