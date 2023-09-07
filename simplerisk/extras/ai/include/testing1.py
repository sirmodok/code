import os
from langchain.agents import initialize_agent, load_tools, AgentType
from langchain.chat_models import ChatOpenAI
from langchain.sql_database import SQLDatabase
from langchain.memory import ConversationBufferMemory
from langchain.agents.agent_toolkits import SQLDatabaseToolkit
from langchain.prompts import MessagesPlaceholder
from langchain.schema import SystemMessage

openai_api_key = os.getenv("OPENAI_API_KEY") 
sql_db_password = os.getenv("SQL_DB_PASSWORD") 
db_user = "simplerisk"
db_host = "localhost"
db_name = "simplerisk"
sql_db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{sql_db_password}@{db_host}/{db_name}")
toolkit = SQLDatabaseToolkit(db=sql_db, llm=ChatOpenAI(temperature=0, model="gpt-3.5-turbo-16k-0613", verbose=True))
tools = toolkit.get_tools()
extra_tools = load_tools(["serpapi", "terminal", "human"])
for i in extra_tools:
    tools.append(i)
content = "You are in charge of a database named simplerisk that contains an enterprise companies risk data. "
agent_kwargs = {"extra_prompt_messages": [MessagesPlaceholder(variable_name="memory")], "system_message": SystemMessage(content=content)}
memory = ConversationBufferMemory(memory_key="memory", return_messages=True)
agent = initialize_agent(tools, llm=ChatOpenAI(temperature=0.5, model="gpt-3.5-turbo-16k-0613", verbose=True), agent=AgentType.OPENAI_FUNCTIONS, verbose=True, agent_kwargs=agent_kwargs, memory=memory)

while True:
    user_input = input("#>")
    result = agent.run(user_input)
    print(result)