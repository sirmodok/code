from flask import Flask, request, render_template
from langchain.llms import OpenAI
from langchain.agents import load_tools, initialize_agent, Tool, AgentExecutor
from langchain.memory import ConversationBufferMemory
from langchain.chat_models import ChatOpenAI
from langchain_experimental.plan_and_execute import PlanAndExecute, load_agent_executor, load_chat_planner
from langchain.tools import DuckDuckGoSearchRun
import constructbot

def scf_search(input_text):
    """Use to search the internet"""
    search = DuckDuckGoSearchRun().run(f"{input_text}")
    print(search)
    return search 

def make_pne_agent():
    planner_temp = 0.9
    planner_model = "gpt-3.5-turbo-instruct"
    planner_llm = OpenAI(model=planner_model, temperature=planner_temp)
    planner = load_chat_planner(planner_llm)

    tools = [
        Tool(name = "scf_search", func=scf_search, description="useful for when you need to find requirements for secure control frameworks"),
    ]
    agent = constructbot.agent()
    executer_llm = AgentExecutor.from_agent_and_tools(agent=agent, tools=tools, verbose=True)
    executor = load_agent_executor(executer_llm, tools=tools)

    agent = PlanAndExecute(planner=planner, executor=executor, verbose=True)

    return agent


class WebApp:
    def __init__(self):
        self.app = Flask(__name__)
        self.app.route("/", methods=["GET", "POST"])(self.index)
        self.app.route("/api/verify", methods=["GET", "POST"])(self.verify)

    def verify(self):
        args = request.args
        prompt = args.get("prompt")
        agent = make_pne_agent()
        result = agent.run(prompt)
        print(result)
        return result

    def index(self):
        return render_template("index.html")

if __name__ == '__main__':
    web_app = WebApp()
    web_app.app.run(debug=True)
