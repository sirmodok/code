import os
from langchain.agents import initialize_agent, load_tools, AgentType
from langchain import OpenAI

class SimpleBot():
    def __init__(self, model: str = "gpt-3.5-turbo-instruct"):
        self.model = model
        self.openai_api_key = os.getenv("OPENAI_API_KEY") 
        self.tools = load_tools(["serpapi",])
        self.llm = OpenAI(temperature=0, model=self.model, verbose=True)
        self.initialize_agent()

    def initialize_agent(self):
        self.agent = initialize_agent(tools=self.tools, llm=self.llm, agent=AgentType.ZERO_SHOT_REACT_DESCRIPTION, verbose=True)
