
from flask import Flask, request, render_template
from flaskwebgui import FlaskUI
import simple
import os


browser_path = "/snap/bin/chromium"
app = Flask(__name__)
ui = FlaskUI(app=app, port=5000, server="flask", width=400, height=900, browser_path=browser_path) 
bot = simple.SimpleBot()

@app.route("/", methods=["GET", "POST"])
def index():
    if request.method == "POST":
        prompt =  request.form["prompt"]
        airesponse = bot.simplebot.run(prompt)
        return {"airesponse": airesponse}
    return render_template("index.html")

if __name__ == '__main__':
    print("""STARTING""")
    ui.run()

