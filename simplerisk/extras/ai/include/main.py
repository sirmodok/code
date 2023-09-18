from flask import Flask, request, render_template
from flaskwebgui import FlaskUI
import simple

class WebApp:
    def __init__(self):
        self.browser_path = "/snap/bin/chromium"
        self.app = Flask(__name__)
        self.ui = FlaskUI(app=self.app, port=5000, server="flask", width=800, height=900, browser_path=self.browser_path) 
        #self.bot = simple.SimpleBot()
        self.app.route("/", methods=["GET", "POST"])(self.index)
        self.app.route("/api", methods=["GET", "POST"])(self.api)
        self.app.route("/api/verify", methods=["GET", "POST"])(self.verify)

    def verify(self):
        result = "error"
        if request.method == "POST":
                query = simple.SimpleBot()
                query.role="""Verify that the following is a secure controls framework"""
                prompt = request.args.get('prompt')
                result = query.agent.run(prompt)
        return '''{}'''.format(result)

    def index(self):
        airesponse = "error"
        if request.method == "POST":
            prompt =  request.form["prompt"]
            try:
                airesponse = self.bot.agent.run(prompt)
            except:
                return{"airesponse": "error with ai response"}
            return {"airesponse": airesponse}
        routes = [str(route) for route in self.app.url_map.iter_rules()]
        return render_template("index.html", routes=routes)

    
    def api(self):
        airesponse = "error"
        if request.method == "POST":
            prompt =   request.args['prompt']
            try:
                airesponse = self.bot.agent.run(prompt)
            except:
                return{"airesponse": "error with ai response"}
            return {"airesponse": airesponse}
        return airesponse

    def run(self):
        print("STARTING")
        self.ui.run()

if __name__ == '__main__':
    web_app = WebApp()
    web_app.run()
