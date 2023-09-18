import os
from langchain.agents import initialize_agent, load_tools, AgentType
from langchain.chat_models import ChatOpenAI
from langchain.memory import ConversationBufferMemory
from langchain.prompts import MessagesPlaceholder
from langchain.schema import SystemMessage

class SimpleBot():
    def __init__(self, role: str = ""):
        self.role = role
        self.model = "gpt-3.5-turbo-16k-0613"
        self.openai_api_key = os.getenv("OPENAI_API_KEY") 
        self.tools = load_tools(["serpapi", "terminal", "human"])
        self.llm = ChatOpenAI(temperature=0, model=self.model, verbose=True)
        self.initialize_agent()

    def initialize_agent(self):
        agent_kwargs = {"extra_prompt_messages": [MessagesPlaceholder(variable_name="memory")], "system_message": SystemMessage(content=self.role)}
        memory = ConversationBufferMemory(memory_key="memory", return_messages=True)
        self.agent = initialize_agent(tools=self.tools, llm=self.llm, agent=AgentType.OPENAI_FUNCTIONS, verbose=True, agent_kwargs=agent_kwargs, memory=memory)
