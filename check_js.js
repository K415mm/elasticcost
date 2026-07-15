const fs = require('fs');
const path = require('path');

// Find all HTML files on the remote server by executing locally after copying or just do it here
const files = fs.readdirSync(__dirname).filter(f => f.startsWith('rendered_') && f.endsWith('.html'));

files.forEach(file => {
    const html = fs.readFileSync(path.join(__dirname, file), 'utf8');
    // Extract scripts
    const scriptRegex = /<script>([\s\S]*?)<\/script>/gi;
    let match;
    let index = 1;
    while ((match = scriptRegex.exec(html)) !== null) {
        const scriptContent = match[1];
        try {
            new Function(scriptContent);
        } catch (err) {
            console.log(`❌ File ${file}, script block #${index} has error: ${err.message}`);
            // Print surrounding lines
            const lines = scriptContent.split('\n');
            // Find line number if available in error stack or parse manually
            console.log("Script snippet causing error:");
            console.log(lines.slice(0, 30).join('\n'));
            console.log("...");
        }
        index++;
    }
});
console.log("Done checking scripts!");
