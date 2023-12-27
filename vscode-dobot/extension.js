const vscode = require('vscode');

/**
 * @param {vscode.ExtensionContext} context
 */
function activate(context) {
	console.log('Dobot extension loaded.');

	vscode.workspace
		.createFileSystemWatcher(`*`)
		.onDidChange(
			function () {
				vscode.window.showInformationMessage('Hello World from The Dobot!');
				// vscode.tasks.executeTask("Dobot Do")
			});

	context.subscriptions.push(
		vscode.commands.registerCommand(
			'dobot.do',
			function () {
				vscode.window.showInformationMessage('Hello World from The Dobot!');
				vscode.tasks.executeTask("Dobot Do")
			}));
}

function deactivate() { }

module.exports = {
	activate,
	deactivate
}
