const vscode = require('vscode');

/**
 * @param {vscode.ExtensionContext} context
 */
function activate(context) {
	console.log('Textmark extension loaded.');

	vscode.workspace
		.createFileSystemWatcher(`*`)
		.onDidChange(
			function () {
				vscode.window.showInformationMessage('Hello World from Textmark!');
				// vscode.tasks.executeTask("Dobot Do")
			});

	context.subscriptions.push(
		vscode.commands.registerCommand(
			'textmark.do',
			function () {
				vscode.window.showInformationMessage('Hello World from Textmark!');
				vscode.tasks.executeTask("Dobot Do")
			}));
}

function deactivate() { }

module.exports = {
	activate,
	deactivate
}
