import sys
import re
import joblib
import os

# Komut dosyasının bulunduğu geçerli dizini al
script_dir = os.path.dirname(os.path.abspath(__file__))

# Debug için
print(f"Script directory: {script_dir}", file=sys.stderr)
print(f"Python path: {sys.executable}", file=sys.stderr)
print(f"PYTHONPATH: {os.environ.get('PYTHONPATH', 'Not set')}", file=sys.stderr)

# Metin temizleme
def clean_text(text):
    if isinstance(text, str):
        # küçük harfe çevir
        text = text.lower()
        # noktalama işaretlerini kaldır
        text = re.sub(r'[^\w\s]', '', text)
        # sayıları kaldır
        text = re.sub(r'\d+', '', text)
        # ekstra boşlukları kaldır
        text = re.sub(r'\s+', ' ', text).strip()
        return text
    return ""

def predict_sentiment(text):
    try:
        # Model ve vecotirzeri yükle
        model_path = os.path.join(script_dir, 'sentiment_model.pkl')
        vectorizer_path = os.path.join(script_dir, 'vectorizer.pkl')
        
        print(f"Loading model from: {model_path}", file=sys.stderr)
        print(f"Loading vectorizer from: {vectorizer_path}", file=sys.stderr)
        
        if not os.path.exists(model_path):
            print(f"Error: Model file not found at {model_path}", file=sys.stderr)
            return "Nötr"
        
        if not os.path.exists(vectorizer_path):
            print(f"Error: Vectorizer file not found at {vectorizer_path}", file=sys.stderr)
            return "Nötr"
        
        model = joblib.load(model_path)
        vectorizer = joblib.load(vectorizer_path)
        
        # yazıyı temizle
        cleaned_text = clean_text(text)
        print(f"Cleaned text: {cleaned_text}", file=sys.stderr)
        
        # yazıyı vektörleştir
        text_tfidf = vectorizer.transform([cleaned_text])
        
        # tahmin et
        prediction = model.predict(text_tfidf)[0]
        
        # tahmin edilen sınıfı al
        label_map = {0: 'Negatif', 1: 'Nötr', 2: 'Pozitif'}
        sentiment = label_map[prediction]
        
        print(f"Predicted sentiment: {sentiment}", file=sys.stderr)
        return sentiment
        
    except Exception as e:
        print(f"Error in prediction: {str(e)}", file=sys.stderr)
        return "Nötr"

# main
if __name__ == "__main__":
    # yazıyı komut satırından al
    if len(sys.argv) < 2:
        print("Hata: Text belirtilmedi.")
        sys.exit(1)
    
    text = ' '.join(sys.argv[1:])
    
    # tahmin et ve sonucu yazdır
    sentiment = predict_sentiment(text)
    print(sentiment)