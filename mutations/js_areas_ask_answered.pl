# Ask the service and hand the component nothing: every panel opens into its error.
s/        event\.detail\.answer = ask\(event\.detail\.poster, event\.detail\.psort\);/        ask(event.detail.poster, event.detail.psort);/;
