<!DOCTYPE html>
<html>
<head>
    <title>Image Test</title>
</head>
<body>
    <h1>Testing Image Access</h1>
    
    <h2>Test 1: Direct image tag</h2>
    <img src="assets/chalkboard.jpg" alt="Chalkboard" style="max-width: 300px;">
    
    <h2>Test 2: Background image test</h2>
    <div style="width: 300px; height: 200px; background-image: url('assets/chalkboard.jpg'); background-size: cover; border: 1px solid black;">
        Background image test
    </div>
    
    <h2>Test 3: CSS file path</h2>
    <div style="width: 300px; height: 200px; background-image: url('assets/css/styles.css'); background-size: cover; border: 1px solid black;">
        CSS file test
    </div>
    
    <h2>File paths:</h2>
    <ul>
        <li>Image: <a href="assets/chalkboard.jpg" target="_blank">assets/chalkboard.jpg</a></li>
        <li>CSS: <a href="assets/css/styles.css" target="_blank">assets/css/styles.css</a></li>
    </ul>
</body>
</html> 