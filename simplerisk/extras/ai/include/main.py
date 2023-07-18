import os
from langchain.chat_models import ChatOpenAI
from langchain.vectorstores import Chroma
from langchain.sql_database import SQLDatabase
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain.embeddings import OpenAIEmbeddings
from pprint import pprint

openai_api_key = os.getenv("OPENAI_API_KEY")
db_password = os.getenv("DB_PASSWORD")

pinecone_location="asia-southeast1-gcp-free"
index_name="simplerisk-test"

embeddings = OpenAIEmbeddings()
embed_model = "text-embedding-ada-002"

db_user = "simplerisk"
db_host = "localhost"
db_name = "simplerisk"

llm = ChatOpenAI(temperature=0, model="gpt-4")
db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{db_password}@{db_host}/{db_name}")


text_splitter = RecursiveCharacterTextSplitter(chunk_size = 2048, chunk_overlap = 20)
full_list = []
tables = db.get_usable_table_names()
for i in tables:
    query = db.run_no_throw("""SELECT * FROM {}""".format(i))
    full_list.append(query)

split = text_splitter.create_documents(full_list)
print(len(split))
db = Chroma.from_documents(split, embeddings)
print(db.similarity_search("How many assets are there?"))
