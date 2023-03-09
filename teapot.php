<?php
    declare(strict_types = 1);
    header("HTTP/1.1 418 I'm a teapot");
    $hidden_div_id = "more_teapots";
?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <meta name="theme-color" content="#66c4a8">
        <title>I'm a teapot</title>
        <link href="resources/index.css" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/css?family=PT+Serif" rel="stylesheet">
        <script type="module">
            const [showMore] = document.querySelectorAll("a[href*='#']");
            showMore.addEventListener("click", e => {
                e.preventDefault();
                const id = "<?= $hidden_div_id ?>";
                const div = document.getElementById(id);
                if (div.classList.contains("hidden")) {
                    div.classList.remove("hidden");
                    location.hash = "#" + id;
                } else {
                    div.classList.add("hidden");
                    window.history.replaceState(null, "", " ");
                    window.scrollTo(0, document.body.scrollHeight);
                }
            });
        </script>
    </head>
    <body>
        <div class="center" style="font-size: 30px;">
            <h2>418 I'm a teapot <img src="./resources/teapot.svg" width="50px" height="50px"></h2>
            <p>Sorry, but I'm unable to process your request because...</p>
            <p>
                I'm a little teapot, <br>
                Short and stout. <br>
                Here is my handle, <br>
                Here is my spout. <br>
                When I get all steamed up, <br>
                Hear me shout: <br>
                "Tip me over and pour me out!" <br>
            </p>
        </div>
        <div class="center">
            <p class="center">Satisfied? Return to the <a href="./">main page</a>.</p>
            <p style="margin-bottom: 20px;">Or click <a href="#<?= $hidden_div_id ?>" id="show_more">here</a> for more teapots (Requires JavaScript)</p>
        </div>
        <div id="<?= $hidden_div_id ?>" class="center hidden" style="font-size: 30px;">
            <p>
                I'm a very special teapot, <br>
                Yes it's true. <br>
                Here's an example of what I can do. <br>
                I can turn my handle into a spout. <br>
                Tip me over and pour me out! <br>
            </p>
        </div>
    </body>
</html>