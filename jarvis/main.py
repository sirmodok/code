import os
from langchain.agents import initialize_agent, load_tools
from langchain.agents import AgentType
from langchain.chat_models import ChatOpenAI
from langchain.prompts import MessagesPlaceholder
from langchain.memory import ConversationBufferMemory
from langchain.schema import SystemMessage

llm = ChatOpenAI(temperature=0, model="gpt-3.5-turbo-0613")
agent_kwargs = {"extra_prompt_messages": [MessagesPlaceholder(variable_name="memory")]}
# agent_kwargs["system_message"] = SystemMessage(content='''You are a digital assistant named Jarvis that lives on my computer and helps automate tasks. 
# Your main job will be taking natural language requests and running the proper commands to accomplish the task. Include error checking. Use /bin/bash for all commands
# If a choice needs to be made ask the user. Answer like a pirate''')
memory = ConversationBufferMemory(memory_key="memory", return_messages=True)
tools = load_tools(["serpapi", "terminal", "python_repl", "requests_all", "dalle-image-generator", "wikipedia", "human"])
agent = initialize_agent(tools, llm, agent=AgentType.ZERO_SHOT_REACT_DESCRIPTION, verbose=True, agent_kwargs=agent_kwargs, memory=memory,)

while True:
    userin=input("*> ")
    result = agent.run(userin)
    print("\n\n\n\n\n{}".format(result))