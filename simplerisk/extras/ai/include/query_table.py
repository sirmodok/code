import os
from langchain.agents import OpenAIFunctionsAgent, tool
from langchain.chat_models import ChatOpenAI
from langchain.sql_database import SQLDatabase
from langchain.agents import load_tools
from langchain.schema import SystemMessage
from langchain.memory import ConversationBufferMemory
from langchain.agents import AgentExecutor


openai_api_key = os.getenv("OPENAI_API_KEY") # Get the openai api key from the OS. Store your openai api key in OPENAI_API_KEY
sql_db_password = os.getenv("SQL_DB_PASSWORD") # Get the SQL database password. Store the database password in SQL_DB_PASSWORD

#Basic information about the database we are querying 
db_user = "simplerisk"
db_host = "localhost"
db_name = "simplerisk"

sql_db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{sql_db_password}@{db_host}/{db_name}") # Create a langchain owned object of our database
memory = ConversationBufferMemory(memory_key="chat_history", return_messages=True) # Create a memory object that stores our conversation history


@tool
def get_sql(query):
    """Runs a query against an sql database"""
    result = sql_db.run(query)
    return result


if __name__ == "__main__":
    tools = load_tools([])# Load up any built in tools needed for the agent
    tools.append(get_sql)
    system_message = SystemMessage(content="You are an assistant that helps with the SimpleRisk application. SimpleRisk is designed to help people maintain security risks in their environment. You will mainly be communicating with an sql database with the schema of 'simplerisk'")
    prompt = OpenAIFunctionsAgent.create_prompt(system_message=system_message)
    main_agent = OpenAIFunctionsAgent(llm=ChatOpenAI(temperature=0, model="gpt-3.5-turbo-0613"), tools=tools, prompt=prompt)
    main_agent_executor = AgentExecutor(agent=main_agent, tools=tools, verbose=True, memory=memory)
    question = input("What can I do you for?")
    main_agent_executor.run(question)
