import requests
import json

url = "http://localhost:8002/recommender/recommend"
data = {
    "category": "Acceso",
    "subcategory": "Control de acceso",
    "price_max": 200000,
    "attributes": {"wifi": True}
}

try:
    print(f"Sending request to {url} with data: {data}")
    response = requests.post(url, json=data)
    print(f"Status Code: {response.status_code}")
    if response.status_code == 200:
        res_json = response.json()
        print("Response Recieved:")
        print(f"LLM Response: {res_json.get('response')}")
        print(f"Products Found: {len(res_json.get('products'))}")
    else:
        print(f"Error: {response.text}")

    # Test Chat
    print("\nTesting Chat...")
    url_chat = "http://localhost:8002/recommender/chat"
    messages = [
        {"role": "system", "content": res_json.get('system_prompt_used')},
        {"role": "user", "content": "Hola, ayudame a elegir."},
        {"role": "assistant", "content": res_json.get('response')},
        {"role": "user", "content": "Que tal es la marca Hikvision?"}
    ]
    response_chat = requests.post(url_chat, json={"messages": messages})
    print(f"Chat Status: {response_chat.status_code}")
    if response_chat.status_code == 200:
        print(f"Chat Response: {response_chat.json().get('response')}")
    else:
        print(f"Chat Error: {response_chat.text}")

except Exception as e:
    print(f"Exception: {e}")
