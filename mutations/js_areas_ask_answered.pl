# Hear the component's question and leave its answer empty: every panel opens into its error.
s/        detail\.answer = client\.ask\(detail\.poster, detail\.psort\);/        client.ask(detail.poster, detail.psort);/;
