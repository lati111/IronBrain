export async function getData(url = "", data = {}) {
    const response = await fetch(url, {
      method: "GET",
      mode: "no-cors",
      cache: "no-cache",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
      },
      redirect: "follow",
      referrerPolicy: "no-referrer",
    });
    return response.json();
}


